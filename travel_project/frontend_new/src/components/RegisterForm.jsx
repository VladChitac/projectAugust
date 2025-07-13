import { useState }   from 'react';
import { useNavigate } from 'react-router-dom';
import api             from '../api';

export default function RegisterForm({ onNeedLogin }) {
  const [username, setUsername] = useState('');
  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [message,  setMessage]  = useState(null);
  const [passwordErrors, setPasswordErrors] = useState([]);
  const navigate = useNavigate();

  const validatePassword = (pwd) => {
    const errors = [];
    
    if (pwd.length < 8) {
      errors.push('Password must be at least 8 characters long');
    }
    
    if (!/[a-zA-Z]/.test(pwd)) {
      errors.push('Password must contain at least one letter');
    }
    
    if (!/[0-9]/.test(pwd)) {
      errors.push('Password must contain at least one number');
    }
    
    return errors;
  };

  const handlePasswordChange = (e) => {
    const newPassword = e.target.value;
    setPassword(newPassword);
    
    if (newPassword) {
      const errors = validatePassword(newPassword);
      setPasswordErrors(errors);
    } else {
      setPasswordErrors([]);
    }
  };

  const handleSubmit = async e => {
    e.preventDefault();
    setMessage(null);

    // Client-side validation
    const passwordValidationErrors = validatePassword(password);
    if (passwordValidationErrors.length > 0) {
      setPasswordErrors(passwordValidationErrors);
      setMessage('Please fix the password requirements');
      return;
    }

    if (username.length < 3) {
      setMessage('Username must be at least 3 characters long');
      return;
    }

    if (!/^[a-zA-Z0-9_.-]+$/.test(username)) {
      setMessage('Username can only contain letters, numbers, dots, hyphens and underscores');
      return;
    }

    try {
      await api.post('/users/register', { username, email, password });
      await api.post('/login', { email, password });

      navigate('/dashboard');
    } catch (err) {
      const msg = err.response?.data?.error || '❌ Registration error';
      setMessage(msg);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {/* Username */}
      <div>
        <input
          type="text"
          placeholder="Username"
          required
          value={username}
          onChange={e => setUsername(e.target.value)}
          className="
            w-full rounded-lg py-3 px-4
            bg-white bg-opacity-90 placeholder-gray-500
            focus:outline-none focus:ring-2 focus:ring-white
          "
        />
        <p className="text-xs text-gray-600 mt-1">
          Username must be 3-50 characters, letters, numbers, dots, hyphens, underscores only
        </p>
      </div>

      {/* Email */}
      <input
        type="email"
        placeholder="Email"
        required
        value={email}
        onChange={e => setEmail(e.target.value)}
        className="
          w-full rounded-lg py-3 px-4
          bg-white bg-opacity-90 placeholder-gray-500
          focus:outline-none focus:ring-2 focus:ring-white
        "
      />

      {/* Password */}
      <div>
        <input
          type="password"
          placeholder="Password"
          required
          value={password}
          onChange={handlePasswordChange}
          className={`
            w-full rounded-lg py-3 px-4
            bg-white bg-opacity-90 placeholder-gray-500
            focus:outline-none focus:ring-2 focus:ring-white
            ${passwordErrors.length > 0 ? 'border-2 border-red-400' : ''}
          `}
        />
        <div className="mt-1">
          <p className="text-xs text-gray-600">
            Password requirements:
          </p>
          <ul className="text-xs mt-1 space-y-1">
            <li className={`${password.length >= 8 ? 'text-green-600' : 'text-red-600'}`}>
              ✓ At least 8 characters
            </li>
            <li className={`${/[a-zA-Z]/.test(password) ? 'text-green-600' : 'text-red-600'}`}>
              ✓ Contains letters
            </li>
            <li className={`${/[0-9]/.test(password) ? 'text-green-600' : 'text-red-600'}`}>
              ✓ Contains numbers
            </li>
          </ul>
          {passwordErrors.length > 0 && (
            <div className="mt-2">
              {passwordErrors.map((error, index) => (
                <p key={index} className="text-xs text-red-600">• {error}</p>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Register button */}
      <button
        type="submit"
        disabled={passwordErrors.length > 0 || !password || !username || !email}
        className={`
          w-full py-3 rounded-full font-semibold
          border-2 border-white text-white
          transition
          ${passwordErrors.length > 0 || !password || !username || !email
            ? 'bg-gray-400 cursor-not-allowed opacity-50'
            : 'bg-[#FF9091] hover:bg-opacity-90'
          }
        `}
      >
        Register
      </button>

      {/* Error message */}
      {message && (
        <p className="text-center text-sm text-red-600 bg-white bg-opacity-90 p-2 rounded">
          {message.replace('Помилка реєстрації', 'Registration error')}
        </p>
      )}

      {/* Links (same style as login) */}
      <div className="mt-6 flex flex-col sm:flex-row sm:justify-between text-sm text-black">
        <span>
          Already have an account?{' '}
          <button
            type="button"
            onClick={onNeedLogin}
            className="
              bg-transparent p-0 m-0
              font-semibold underline hover:text-[#d14b4c]
              focus:outline-none
            "
          >
            Log in
          </button>
        </span>
      </div>
    </form>
  );
}
